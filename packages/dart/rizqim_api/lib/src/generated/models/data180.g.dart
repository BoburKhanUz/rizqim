// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data180.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data180 _$Data180FromJson(Map<String, dynamic> json) => Data180(
  changed: json['changed'] as String,
  brandId: (json['brand_id'] as num?)?.toInt(),
);

Map<String, dynamic> _$Data180ToJson(Data180 instance) => <String, dynamic>{
  'changed': instance.changed,
  'brand_id': instance.brandId,
};
