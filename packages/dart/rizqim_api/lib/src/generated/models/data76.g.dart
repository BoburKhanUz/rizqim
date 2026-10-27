// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data76.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data76 _$Data76FromJson(Map<String, dynamic> json) => Data76(
  id: (json['id'] as num).toInt(),
  branchId: (json['branch_id'] as num).toInt(),
  stationId: (json['station_id'] as num).toInt(),
  categoryId: (json['category_id'] as num?)?.toInt(),
  categoryName: json['category_name'] as String?,
  productId: (json['product_id'] as num?)?.toInt(),
  productName: json['product_name'] as String?,
);

Map<String, dynamic> _$Data76ToJson(Data76 instance) => <String, dynamic>{
  'id': instance.id,
  'branch_id': instance.branchId,
  'station_id': instance.stationId,
  'category_id': instance.categoryId,
  'category_name': instance.categoryName,
  'product_id': instance.productId,
  'product_name': instance.productName,
};
