import {__} from '@wordpress/i18n';
import {Button, Spinner, Icon} from '@wordpress/components';
import {chevronRight, chevronDown, folder as folderIcon} from
        '@wordpress/icons';

export interface FolderOption {
    name: string;
    path: string;
}

export interface FolderTreeNodeProps {
    folder: FolderOption;
    depth: number;
    selectedPath: string;
    expandedPaths: string[];
    childrenMap: Record<string, FolderOption[]>;
    loadingPaths: string[];
    onToggle: (path: string) => void;
    onSelect: (path: string) => void;
}

export default function FolderTreeNode({
                                           folder,
                                           depth,
                                           selectedPath,
                                           expandedPaths,
                                           childrenMap,
                                           loadingPaths,
                                           onToggle,
                                           onSelect,
                                       }: FolderTreeNodeProps) {
    const isExpanded = expandedPaths.includes(folder.path);
    const isSelected = selectedPath === folder.path;
    const isLoading = loadingPaths.includes(folder.path);
    const children = childrenMap[folder.path];

    // undefined = noch nicht geladen = Pfeil zeigen
    // [] = geladen, leer = kein Pfeil
    const hasChildren = children === undefined || children.length
        > 0;

    return (
        <div>
            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: '4px',
                    paddingLeft: depth * 16 + 'px',
                    paddingTop: '2px',
                    paddingBottom: '2px',
                }}
            >
                {hasChildren ? (
                    <Button
                        icon={isExpanded ? chevronDown :
                            chevronRight}
                        size="small"
                        label={isExpanded ? __('Collapse',
                            'rrze-faubox') : __('Expand', 'rrze-faubox')}
                        onClick={() => onToggle(folder.path)}
                        style={{minWidth: '24px', padding: '0'}}
                    />
                ) : (
                    <span style={{minWidth: '24px', display:
                            'inline-block'}} />
                )}
                <Icon
                    icon={folderIcon}
                    size={16}
                    style={{color: isSelected ? '#007cba' :
                            '#666', flexShrink: 0}}
                />
                <span
                    role="button"
                    tabIndex={0}
                    onClick={() => onSelect(folder.path)}
                    onKeyDown={(e) => e.key === 'Enter' &&
                        onSelect(folder.path)}
                    style={{
                        cursor: 'pointer',
                        fontWeight: isSelected ? '600' :
                            'normal',
                        color: isSelected ? '#007cba' :
                            'inherit',
                        flex: 1,
                        userSelect: 'none',
                    }}
                >
                      {folder.name}
                  </span>
            </div>
            {isLoading && (
                <div style={{paddingLeft: (depth + 1) * 16 + 4 +
                        'px'}}>
                    <Spinner />
                </div>
            )}
            {isExpanded && !isLoading && children &&
                children.map((child) => (
                    <FolderTreeNode
                        key={child.path}
                        folder={child}
                        depth={depth + 1}
                        selectedPath={selectedPath}
                        expandedPaths={expandedPaths}
                        childrenMap={childrenMap}
                        loadingPaths={loadingPaths}
                        onToggle={onToggle}
                        onSelect={onSelect}
                    />
                ))}
        </div>
    );
}
