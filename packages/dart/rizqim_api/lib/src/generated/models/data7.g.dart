// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data7.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data7 _$Data7FromJson(Map<String, dynamic> json) => Data7(
  storeId: (json['store_id'] as num).toInt(),
  branches: (json['branches'] as List<dynamic>)
      .map((e) => Branches.fromJson(e as Map<String, dynamic>))
      .toList(),
  generatedAt: json['generated_at'] as String?,
);

Map<String, dynamic> _$Data7ToJson(Data7 instance) => <String, dynamic>{
  'store_id': instance.storeId,
  'branches': instance.branches,
  'generated_at': instance.generatedAt,
};
