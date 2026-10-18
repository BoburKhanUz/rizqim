import os
import glob

def revert_in_file(filepath):
    with open(filepath, 'r') as f:
        content = f.read()
    original = content

    # Forms back to Schemas
    content = content.replace(r'Filament\Forms\Form', r'Filament\Schemas\Schema')
    content = content.replace(r'Filament\Forms\Components\Wizard\Step', r'Filament\Schemas\Components\Wizard\Step')
    content = content.replace(r'Filament\Forms\Components\Tabs\Tab', r'Filament\Schemas\Components\Tabs\Tab')
    content = content.replace(r'use Filament\Forms;', r'use Filament\Schemas;')
    content = content.replace(r'public static function form(Form $form): Form', r'public static function form(Schema $schema): Schema')
    content = content.replace(r'public static function configure(\Filament\Forms\Form $form): \Filament\Forms\Form', r'public static function configure(Schema $schema): Schema')
    content = content.replace(r'public function form(\Filament\Forms\Form $form): \Filament\Forms\Form', r'public function form(Schema $schema): Schema')
    content = content.replace(r'public static function configure(\Filament\Schemas\Schema $form): \Filament\Schemas\Schema', r'public static function configure(Schema $schema): Schema')

    # Revert specific variables
    content = content.replace(r'return $form', r'return $schema')

    # Infolists back to Schemas
    content = content.replace(r'public static function infolist(\Filament\Infolists\Infolist $infolist): \Filament\Infolists\Infolist', r'public static function infolist(Schema $schema): Schema')
    content = content.replace(r'\Filament\Infolists\Infolist $infolist', r'Schema $schema')
    content = content.replace(r'\Filament\Infolists\Infolist', r'Filament\Schemas\Schema')
    content = content.replace(r'return $infolist', r'return $schema')
    content = content.replace(r'use Filament\Infolists\Infolist;', r'use Filament\Schemas\Schema;')

    # Components
    content = content.replace(r'\Filament\Infolists\Components\Grid', r'\Filament\Schemas\Components\Grid')
    content = content.replace(r'\Filament\Infolists\Components\Section', r'\Filament\Schemas\Components\Section')
    
    content = content.replace(r'\Filament\Forms\Components\Grid', r'\Filament\Schemas\Components\Grid')
    content = content.replace(r'\Filament\Forms\Components\Section', r'\Filament\Schemas\Components\Section')
    content = content.replace(r'Filament\Forms\Components\Section', r'Filament\Schemas\Components\Section')
    content = content.replace(r'Forms\Components\Section', r'Schemas\Components\Section')
    content = content.replace(r'use Filament\Forms\Components\Group;', r'use Filament\Schemas\Components\Group;')

    if original != content:
        with open(filepath, 'w') as f:
            f.write(content)
        print(f"Reverted: {filepath}")

for root, dirs, files in os.walk('/home/boburkhan/Desktop/order-mini-app/backend/app/Filament'):
    for file in files:
        if file.endswith('.php'):
            revert_in_file(os.path.join(root, file))
