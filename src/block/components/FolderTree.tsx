import {__} from '@wordpress/i18n';
import {Spinner} from '@wordpress/components';
import FolderTreeNode, {FolderOption} from './FolderTreeNode';

export interface FolderTreeProps {
    rootFolders: FolderOption[];
    rootLoading: boolean;
    loadError: boolean;
    noFolderConfigured: boolean;
    selectedPath: string;
    expandedPaths: string[];
    childrenMap: Record<string, FolderOption[]>;
    loadingPaths: string[];
    onToggle: (path: string) => void;
    onSelect: (path: string) => void;
}

export default function FolderTree({
                                       rootFolders,
                                       rootLoading,
                                       loadError,
                                       noFolderConfigured,
                                       selectedPath,
                                       expandedPaths,
                                       childrenMap,
                                       loadingPaths,
                                       onToggle,
                                       onSelect,
                                   }: FolderTreeProps) {
    const selectedLabel = selectedPath
        ? selectedPath.split('/').filter(Boolean).pop() ??
        selectedPath
        : '';

    return (
        <div>
            {rootLoading && <Spinner/>}
            {noFolderConfigured && (
                <p style={{color: 'red'}}>
                    {__('No main folder configured. Please check your FAUbox settings!', 'rrze-faubox')}
                </p>
            )}
            {loadError && (
                <p style={{color: 'red'}}>
                    {__('Folders could not be loaded. Please check your FAUbox settings!', 'rrze-faubox')}
                </p>
            )}
            {!rootLoading && !loadError && !noFolderConfigured &&
                (
                    <div
                        style={{
                            border: '1px solid #949494',
                            borderRadius: '2px',
                            padding: '5px',
                            maxHeight: '300px',
                            overflowY: 'auto',
                            background: '#fff',
                        }}
                    >
                        {rootFolders.length === 0 && (
                            <p style={{
                                color: '#666', fontSize:
                                    '13px', margin: 0
                            }}>
                                {__('No folders found.',
                                    'rrze-faubox')}
                            </p>
                        )}
                        {rootFolders.map((folder) => (
                            <FolderTreeNode
                                key={folder.path}
                                folder={folder}
                                depth={0}
                                selectedPath={selectedPath}
                                expandedPaths={expandedPaths}
                                childrenMap={childrenMap}
                                loadingPaths={loadingPaths}
                                onToggle={onToggle}
                                onSelect={onSelect}
                            />
                        ))}
                    </div>
                )}
            {selectedPath && (
                <p style={{
                    marginTop: '10px', fontSize: '14px',
                    color: '#007cba'
                }}>
                    {__('Selected File', 'rrze-faubox')}:{''}<strong>{selectedLabel}</strong>
                </p>
            )}
        </div>
    );
}
