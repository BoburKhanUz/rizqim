// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data5 _$Data5FromJson(Map<String, dynamic> json) => Data5(
  storeId: (json['store_id'] as num).toInt(),
  branches: (json['branches'] as List<dynamic>)
      .map((e) => Branches.fromJson(e as Map<String, dynamic>))
      .toList(),
  generatedAt: json['generated_at'] as String?,
);

Map<String, dynamic> _$Data5ToJson(Data5 instance) => <String, dynamic>{
  'store_id': instance.storeId,
  'branches': instance.branches,
  'generated_at': instance.generatedAt,
};
