import {__} from '@wordpress/i18n';
import {Spinner} from '@wordpress/components';
import {useState, useEffect, Fragment} from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Icon, folder } from '@wordpress/icons';

declare const rrze_faubox_data: { settings_url?: string; folder?: string
};

interface FolderOption {
    name: string;
    path: string;
}

interface Breadcrumb {
    name: string;
    path: string;
}

export interface FolderTreeProps {
    selectedPath: string;
    onSelect: (path: string) => void;
}

export default function FolderTree({selectedPath, onSelect}: FolderTreeProps) {
    const [currentFolders, setCurrentFolders] = useState<FolderOption[]>([]);
    const [breadcrumbs, setBreadcrumbs] = useState<Breadcrumb[]>([]);
    const [loading, setLoading] = useState<boolean>(false);
    const [error, setError] = useState<boolean>(false);
    const [noFolderConfigured, setNoFolderConfigured] = useState<boolean>(false);
    const [emptyFolderPath, setEmptyFolderPath] = useState<string | null>(null);

    const fetchFolders = (path: string) => {
        setLoading(true);
        setError(false);

        // Bug fix: backticks instead of single quotes for template literal
        const endpoint = path
            ? `/rrze-faubox/v1/folders?path=${encodeURIComponent(path)}`
            : '/rrze-faubox/v1/folders';

        apiFetch<FolderOption[]>({path: endpoint})
            .then((data) => {
                setCurrentFolders(Array.isArray(data) ? data : []);
                setLoading(false);
            })
            .catch((err: { code?: string }) => {
                if (err?.code === 'no_folder_configured') {
                    setNoFolderConfigured(true);
                } else {
                    setError(true);
                }
                setLoading(false);
            });
    };

    useEffect(() => {
        fetchFolders('');
    }, []);

    const handleNavigate = (folder: FolderOption) => {
        setLoading(true);
        setError(false);
        setEmptyFolderPath(null);

        apiFetch<FolderOption[]>({path: `/rrze-faubox/v1/folders?path=${encodeURIComponent(folder.path)}`})
            .then((data) => {
                const folders = Array.isArray(data) ? data : [];
                if (folders.length === 0) {
                    setEmptyFolderPath(folder.path);
                } else {
                    setBreadcrumbs([...breadcrumbs, {name: folder.name, path: folder.path}]);
                    setCurrentFolders(folders);
                }
                setLoading(false);
            })
            .catch(() => {
                setError(true);
                setLoading(false);
            });
    };

    const handleBack = () => {
        setEmptyFolderPath(null);
        if (breadcrumbs.length === 1) {
            setBreadcrumbs([]);
            fetchFolders('');
        } else {
            const parentCrumb = breadcrumbs[breadcrumbs.length - 2];
            setBreadcrumbs(breadcrumbs.slice(0, -1));
            fetchFolders(parentCrumb.path);
        }
    };

    const handleBreadcrumbClick = (index: number | null) => {
        setEmptyFolderPath(null);
        if (index === null) {
            setBreadcrumbs([]);
            fetchFolders('');
        } else {
            const crumb = breadcrumbs[index];
            setBreadcrumbs(breadcrumbs.slice(0, index + 1));
            fetchFolders(crumb.path);
        }
    };

    const selectedLabel = selectedPath
        ? selectedPath.split('/').filter(Boolean).pop() ?? selectedPath
        : '';

    return (
        <div>
            {/* Breadcrumb */}
            <div style={{
                display: 'flex',
                flexWrap: 'wrap',
                alignItems: 'center',
                gap: '2px',
                marginBottom: '6px',
                fontSize: '13px',
            }}>
                <button
                    onClick={() => handleBreadcrumbClick(null)}
                    style={{
                        background: 'none',
                        border: 'none',
                        cursor: 'pointer',
                        padding: '2px 4px',
                        color: breadcrumbs.length === 0 ? '#1e1e1e' : '#007cba',
                        fontWeight: breadcrumbs.length === 0 ? '600' : 'normal',
                        fontSize: '13px',
                    }}
                >
                    {rrze_faubox_data?.folder || __('Root', 'rrze-faubox')}
                </button>
                {breadcrumbs.map((crumb, index) => (
                    <span key={crumb.path} style={{display: 'flex', alignItems: 'center', gap: '2px'}}>
                          <span style={{color: '#999'}}>›</span>
                          <button
                              onClick={() => handleBreadcrumbClick(index)}
                              style={{
                                  background: 'none',
                                  border: 'none',
                                  cursor: 'pointer',
                                  padding: '2px 4px',
                                  color: index === breadcrumbs.length - 1 ? '#1e1e1e' : '#007cba',
                                  fontWeight: index === breadcrumbs.length - 1 ? '600' : 'normal',
                                  fontSize: '13px',
                              }}
                          >
                              {crumb.name}
                          </button>
                      </span>
                ))}
            </div>

            {/* Back button */}
            {breadcrumbs.length > 0 && (
                <button
                    onClick={handleBack}
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: '4px',
                        background: 'none',
                        border: '1px solid #949494',
                        borderRadius: '2px',
                        cursor: 'pointer',
                        padding: '3px 8px',
                        marginBottom: '8px',
                        marginTop: '8px',
                        fontSize: '13px',
                        color: 'inherit',
                    }}
                >
                    ← {__('back', 'rrze-faubox')}
                </button>
            )}

            {/* Folder list */}
            {loading && <Spinner />}
            {noFolderConfigured && (
                <p style={{color: 'red', fontSize: '13px'}}>
                    {__('No main folder configured. Please check your FAUbox settings!', 'rrze-faubox')}
                </p>
            )}
            {error && (
                <p style={{color: 'red', fontSize: '13px'}}>
                    {__('Folders could not be loaded. Please check your FAUbox settings!', 'rrze-faubox')}
                </p>
            )}
            {!loading && !error && !noFolderConfigured && currentFolders.length > 0 && (
                <div style={{
                    border: '1px solid #949494',
                    borderRadius: '2px',
                    maxHeight: '250px',
                    overflowY: 'auto',
                    background: '#fff',
                    maxWidth: '320px'
                }}>
                    {currentFolders.map((folder) => {
                        const isSelected = selectedPath === folder.path;
                        return (
                            <Fragment key={folder.path}>
                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        borderBottom: emptyFolderPath === folder.path ? 'none' : '1px solid #f0f0f0',
                                        background: isSelected ? '#e8f4fb' : 'none',
                                    }}
                                >
                                    {/* Navigate into folder */}
                                    <button
                                        onClick={() => handleNavigate(folder)}
                                        style={{
                                            flex: 1,
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: '6px',
                                            padding: '7px 8px',
                                            background: 'none',
                                            border: 'none',
                                            cursor: 'pointer',
                                            textAlign: 'left',
                                            fontSize: '13px',
                                            color: isSelected ? '#007cba' : '#1e1e1e',
                                            fontWeight: isSelected ? '600' : 'normal',
                                        }}
                                    >
                                        <span>📁</span>
                                        <span>{folder.name}</span>
                                        <span style={{color: 'inherit', fontSize: '16px'}}>›</span>
                                    </button>

                                    {/* Select this folder */}
                                    <button
                                        onClick={() => onSelect(folder.path)}
                                        title={__('Select this folder', 'rrze-faubox')}
                                        style={{
                                            flexShrink: 0,
                                            background: isSelected ? '#007cba' : '#ddeef8',
                                            border: 'none',
                                            borderLeft: '1px solid #ddd',
                                            cursor: 'pointer',
                                            padding: '7px 10px',
                                            marginRight: '3px',
                                            color: isSelected ? '#fff' : '#007cba',
                                            fontSize: '13px',
                                            lineHeight: 1,
                                        }}
                                    >
                                        ✓
                                    </button>
                                </div>
                                {emptyFolderPath === folder.path && (
                                    <div style={{
                                        padding: '4px 8px 6px 8px',
                                        fontSize: '12px',
                                        color: '#666',
                                        fontStyle: 'italic',
                                        borderBottom: '1px solid #f0f0f0',
                                        background: '#fafafa',
                                    }}>
                                        {__('This folder has no subfolders. Use ✓ to select it.', 'rrze-faubox')}
                                    </div>
                                )}
                            </Fragment>
                        );
                    })}
                </div>
            )}


            {/* Selected folder indicator */}
            {selectedPath && (
                <p style={{marginTop: '10px', fontSize: '14px', color: '#007cba'}}>
                    {__('Selected Folder', 'rrze-faubox')}: <br/><strong>{selectedLabel}</strong>
                </p>
            )}
        </div>
    );
}
