import xml.etree.ElementTree as ET

regions_tree = ET.parse('/tmp/uz-regions/src/database/data/regions.xml')
districts_tree = ET.parse('/tmp/uz-regions/src/database/data/districts.xml')

regions_map = {}
for region in regions_tree.getroot().find('table_regions').findall('regions'):
    r_id = region.get('id')
    r_name = region.get('name_uz')
    regions_map[r_id] = {'name': r_name, 'districts': []}

for district in districts_tree.getroot().find('table_districts').findall('districts'):
    r_id = district.get('region_id')
    d_name = district.get('name_uz')
    if r_id in regions_map:
        regions_map[r_id]['districts'].append(d_name)

output = "        $regions = [\n"
for r_id, r_data in regions_map.items():
    r_name = r_data['name'].replace("'", "\\'")
    districts_list = ", ".join([f"'{d.replace(chr(39), chr(92)+chr(39))}'" for d in r_data['districts']])
    output += f"            '{r_name}' => [{districts_list}],\n"
output += "        ];"

with open('/tmp/seeder_data.txt', 'w') as f:
    f.write(output)
