import {__} from '@wordpress/i18n';
import {Spinner, Button, Icon} from '@wordpress/components';
import {useState, useEffect, Fragment} from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import {rotateRight, chevronRight, chevronLeft} from '@wordpress/icons';


declare const rrze_faubox_data: {
    settings_url?: string; folder?: string
};

interface FolderOption {
    name: string;
    path: string;
    hasChildren?: boolean;
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
    const [indexNotBuilt, setIndexNotBuilt] = useState<boolean>(false);
    const [refreshing, setRefreshing] = useState<boolean>(false);
    const [refreshError, setRefreshError] = useState<string | null>(null);


    const fetchFolders = (path: string) => {
        setLoading(true);
        setError(false);
        setIndexNotBuilt(false);
        setRefreshError(null);


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
                } else if (err?.code === 'index_not_built') {
                    setIndexNotBuilt(true);
                } else {
                    setError(true);
                }
                setLoading(false);
            });
    };

    useEffect(() => {
        if (!selectedPath) {
            fetchFolders('');
            return;
        }

        const parts = selectedPath.split('/');
        const rootFolder = rrze_faubox_data?.folder || '';
        const rootParts = rootFolder.split('/').filter(Boolean);
        const parentParts = parts.slice(0, -1);

        if (parentParts.length <= rootParts.length) {
            // Selected folder is a direct child of root — show root level
            fetchFolders('');
            return;
        }

        // Reconstruct breadcrumbs from path segments between root and parent
        const crumbs: Breadcrumb[] = [];
        for (let i = rootParts.length; i < parentParts.length; i++) {
            crumbs.push({
                name: parentParts[i],
                path: parentParts.slice(0, i + 1).join('/'),
            });
        }
        setBreadcrumbs(crumbs);
        fetchFolders(parentParts.join('/'));
    }, []);


    const handleRefreshIndex = () => {
        setRefreshing(true);
        setRefreshError(null);

        apiFetch({
            path: '/rrze-faubox/v1/index/refresh',
            method: 'POST',
        })
            .then(() => {
                setIndexNotBuilt(false);
                fetchFolders('');
            })
            .catch((err: { data?: { status?: number } }) => {
                if (err?.data?.status === 429) {
                    setRefreshError(__('Please wait before refreshing again.', 'rrze-faubox'));
                } else {
                    setRefreshError(__('Index refresh failed. Please try again.', 'rrze-faubox'));
                }
            })
            .finally(() => {
                setRefreshing(false);
            });
    };

    const handleNavigate = (folder: FolderOption) => {
        setLoading(true);
        setError(false);

        apiFetch<FolderOption[]>({path: `/rrze-faubox/v1/folders?path=${encodeURIComponent(folder.path)}`})
            .then((data) => {
                const folders = Array.isArray(data) ? data : [];
                if (folders.length > 0) {
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
                gap: '0',
                marginBottom: '8px',
                fontSize: '12px',
                color: '#757575',
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
                        fontSize: '12px',
                    }}
                >
                    {rrze_faubox_data?.folder || __('Root', 'rrze-faubox')}
                </button>
                {breadcrumbs.map((crumb, index) => (
                    <span key={crumb.path} style={{
                        display: 'flex', alignItems:
                            'center'
                    }}>
                          <span style={{color: '#888', flexShrink: 0, display: 'flex', alignItems: 'center'
                          }}>
                          <Icon icon={chevronRight} size={18}/>
                          </span>
                          <button
                              onClick={() => handleBreadcrumbClick(index)}
                              style={{
                                  background: 'none',
                                  border: 'none',
                                  cursor: 'pointer',
                                  padding: '2px 4px',
                                  color: index === breadcrumbs.length - 1 ? '#1e1e1e' : '#007cba',
                                  fontWeight: index === breadcrumbs.length - 1 ? '600' : 'normal',
                                  fontSize: '12px',
                              }}
                          >
                              {crumb.name}
                          </button>
                      </span>
                ))}
            </div>

            {/* Back button */}
            {breadcrumbs.length > 0 && (
                <Button
                    variant="tertiary"
                    icon={chevronLeft}
                    iconSize={15}
                    onClick={handleBack}
                    style={{
                        marginBottom: '5px',
                        marginLeft: '2px',
                        marginTop: '8px',
                        height: '25px',
                        fontSize: '12px',
                        padding: '4px 4px',
                        //border: '1px solid #e0e0e0',
                        //borderRadius: '2px',
                    }}
                >
                    {__('Back', 'rrze-faubox')}
                </Button>
            )}

            {/* Folder list */}
            {loading && <Spinner/>}
            {noFolderConfigured && (
                <p style={{color: '#cc1818', fontSize: '13px'}}>
                    {__('No main folder configured. Please check your FAUbox settings!', 'rrze-faubox')}
                </p>
            )}
            {error && (
                <p style={{color: '#cc1818', fontSize: '13px'}}>
                    {__('Folders could not be loaded. Please check your FAUbox settings!', 'rrze-faubox')}
                </p>
            )}

            {/* Index not built yet */}
            {indexNotBuilt && (
                <div style={{fontSize: '13px', marginBottom: '8px'}}>
                    <p style={{color: '#996800', marginBottom: '8px'}}>
                        {__('Folder index not built yet. Please save your FAUbox settings or refresh the index manually.', 'rrze-faubox')}
                    </p>
                    <Button
                        variant="secondary"
                        icon={rotateRight}
                        isBusy={refreshing}
                        disabled={refreshing}
                        onClick={handleRefreshIndex}
                    >
                        {refreshing ? __('Refreshing…', 'rrze-faubox') : __('Refresh index', 'rrze-faubox')}
                    </Button>
                    {refreshError && (
                        <p style={{
                            color: '#cc1818', marginTop: '6px', fontSize:
                                '12px'
                        }}>{refreshError}</p>
                    )}
                </div>
            )}
            {/* Folder list */}
            {!loading && !error && !noFolderConfigured && currentFolders.length > 0
                && (
                    <>
                        <div className="rrze-faubox-folder-list" style={{
                            border: '1px solid #e0e0e0',
                            borderRadius: '2px',
                            maxHeight: '260px',
                            overflowY: 'scroll',
                            background: '#fff',
                            boxShadow: '0 1px 3px rgba(0,0,0,0.06)',
                            maxWidth: '550px',
                        }}>
                            {currentFolders.map((folder) => {
                                const isSelected = selectedPath === folder.path;
                                return (
                                    <Fragment key={folder.path}>
                                        <div style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            borderBottom: '1px solid #f0f0f0',
                                            background: isSelected ? '#f0f7fd' : 'transparent',
                                        }}>
                                            {/* Navigate into folder */}
                                            <button
                                                onClick={() => folder.hasChildren !== false && handleNavigate(folder)}
                                                style={{
                                                    flex: 1,
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    gap: '8px',
                                                    padding: '10px 10px',
                                                    background: 'none',
                                                    border: 'none',
                                                    cursor: folder.hasChildren !== false ? 'pointer' : 'default',
                                                    textAlign: 'left',
                                                    fontSize: '13px',
                                                    color: isSelected ? '#007cba' : '#1e1e1e',
                                                    fontWeight: isSelected ? '600' : 'normal',
                                                }}
                                            >
                                                <span>📁</span>
                                                <span style={{flex: 1}}>{folder.name}</span>
                                                {folder.hasChildren !== false && (
                                                    <span style={{color: '#888', flexShrink: 0, display: 'flex', alignItems: 'center'
                                                    }}>
                                                     <Icon icon={chevronRight} size={18}/>
                                                        </span>
                                                )}
                                            </button>

                                            {/* Select this folder */}
                                            <button
                                                onClick={() => onSelect(folder.path)}
                                                title={__('Select this folder', 'rrze-faubox')}
                                                style={{
                                                    flexShrink: 0,
                                                    background: isSelected ? '#007cba' : 'transparent',
                                                    border: 'none',
                                                    borderRadius: '2px',
                                                    borderLeft: '1px solid #e0e0e0',
                                                    cursor: 'pointer',
                                                    padding: '8px 12px',
                                                    color: isSelected ? '#fff' : '#007cba',
                                                    fontSize: '16px',
                                                    lineHeight: 1,
                                                }}
                                            >
                                                ✓
                                            </button>
                                        </div>
                                    </Fragment>
                                );
                            })}
                        </div>

                        {/* Refresh button below folder list */}
                        <div style={{marginTop: '8px'}}>
                            <Button
                                variant="link"
                                icon={rotateRight}
                                isBusy={refreshing}
                                disabled={refreshing}
                                onClick={handleRefreshIndex}
                                style={{fontSize: '12px'}}
                            >
                                {refreshing ? __('Refreshing…', 'rrze-faubox') : __('Refresh index', 'rrze-faubox')}
                            </Button>
                            {refreshError && (
                                <p style={{color: '#cc1818', fontSize: '12px', marginTop: '4px'}}>
                                    {refreshError}
                                </p>
                            )}
                        </div>
                    </>
                )}

            {/* Selected folder indicator */}
            {selectedPath && (
                <p style={{
                    marginTop: '10px',
                    fontSize: '13px',
                    color: '#007cba',
                    borderTop: '1px solid #f0f0f0',
                    paddingTop: '10px',
                }}>
                    {__('Selected Folder', 'rrze-faubox')}:{' '}
                    <strong>{selectedLabel}</strong>
                </p>
            )}
        </div>
    );
}

