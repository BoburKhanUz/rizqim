// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'products_missing_mxik.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ProductsMissingMxik _$ProductsMissingMxikFromJson(Map<String, dynamic> json) =>
    ProductsMissingMxik(
      id: (json['id'] as num).toInt(),
      uuid: json['uuid'] as String,
      name: json['name'] as String,
      kind: json['kind'] as String,
      status: json['status'] as String,
      categoryId: (json['category_id'] as num).toInt(),
    );

Map<String, dynamic> _$ProductsMissingMxikToJson(
  ProductsMissingMxik instance,
) => <String, dynamic>{
  'id': instance.id,
  'uuid': instance.uuid,
  'name': instance.name,
  'kind': instance.kind,
  'status': instance.status,
  'category_id': instance.categoryId,
};
