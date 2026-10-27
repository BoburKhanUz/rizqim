// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data27.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data27 _$Data27FromJson(Map<String, dynamic> json) => Data27(
  storeId: (json['store_id'] as num).toInt(),
  branches: (json['branches'] as List<dynamic>)
      .map((e) => Branches.fromJson(e as Map<String, dynamic>))
      .toList(),
  generatedAt: json['generated_at'] as String?,
);

Map<String, dynamic> _$Data27ToJson(Data27 instance) => <String, dynamic>{
  'store_id': instance.storeId,
  'branches': instance.branches,
  'generated_at': instance.generatedAt,
};
