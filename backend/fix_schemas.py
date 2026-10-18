import os
import glob

def replace_in_file(filepath):
    with open(filepath, 'r') as f:
        content = f.read()

    original = content
    # Common Form replacements
    content = content.replace(r'Filament\Schemas\Schema', r'Filament\Forms\Form')
    content = content.replace(r'Filament\Schemas\Components\Wizard\Step', r'Filament\Forms\Components\Wizard\Step')
    content = content.replace(r'Filament\Schemas\Components\Tabs\Tab', r'Filament\Forms\Components\Tabs\Tab')
    content = content.replace(r'use Filament\Schemas;', r'use Filament\Forms;')
    content = content.replace(r'public static function form(Schema $schema): Schema', r'public static function form(Form $form): Form')
    content = content.replace(r'public static function form(Schema $form): Schema', r'public static function form(Form $form): Form')
    content = content.replace(r'return $schema', r'return $form')
    
    # Infolist replacements (usually they are inside infolist function)
    content = content.replace(r'public static function infolist(Schema $schema): Schema', r'public static function infolist(\Filament\Infolists\Infolist $infolist): \Filament\Infolists\Infolist')
    content = content.replace(r'public static function infolist(\Filament\Forms\Form $schema): \Filament\Forms\Form', r'public static function infolist(\Filament\Infolists\Infolist $infolist): \Filament\Infolists\Infolist')
    content = content.replace(r'public static function infolist(Schema $infolist): Schema', r'public static function infolist(\Filament\Infolists\Infolist $infolist): \Filament\Infolists\Infolist')
    
    # In Infolists, change Schemas\Components to Infolists\Components
    if 'public static function infolist' in content:
        # We need to specifically replace Schemas inside the infolist method
        # A bit tricky, but mostly these are fully qualified:
        content = content.replace(r'\Filament\Schemas\Components\Grid', r'\Filament\Infolists\Components\Grid')
        content = content.replace(r'\Filament\Schemas\Components\Section', r'\Filament\Infolists\Components\Section')
    
    # Otherwise they might be Form components
    content = content.replace(r'\Filament\Schemas\Components\Grid', r'\Filament\Forms\Components\Grid')
    content = content.replace(r'\Filament\Schemas\Components\Section', r'\Filament\Forms\Components\Section')
    content = content.replace(r'Filament\Schemas\Components\Section', r'Filament\Forms\Components\Section')
    
    # Leftovers
    content = content.replace(r'Schemas\Components\Section', r'Forms\Components\Section')

    if original != content:
        with open(filepath, 'w') as f:
            f.write(content)
        print(f"Updated: {filepath}")

for root, dirs, files in os.walk('/home/boburkhan/Desktop/order-mini-app/backend/app/Filament'):
    for file in files:
        if file.endswith('.php'):
            replace_in_file(os.path.join(root, file))
