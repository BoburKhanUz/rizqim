// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data144.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data144 _$Data144FromJson(Map<String, dynamic> json) => Data144(
  id: (json['id'] as num).toInt(),
  uuid: json['uuid'] as String,
  name: json['name'] as String,
  perPersonPrice: json['per_person_price'] as String,
  minimumGuests: (json['minimum_guests'] as num).toInt(),
  included: (json['included'] as List<dynamic>)
      .map((e) => e as String)
      .toList(),
  description: json['description'] as String?,
  isActive: json['is_active'] as bool,
  sortOrder: (json['sort_order'] as num).toInt(),
  items: (json['items'] as List<dynamic>)
      .map((e) => Items5.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data144ToJson(Data144 instance) => <String, dynamic>{
  'id': instance.id,
  'uuid': instance.uuid,
  'name': instance.name,
  'per_person_price': instance.perPersonPrice,
  'minimum_guests': instance.minimumGuests,
  'included': instance.included,
  'description': instance.description,
  'is_active': instance.isActive,
  'sort_order': instance.sortOrder,
  'items': instance.items,
};
