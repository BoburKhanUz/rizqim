// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data6.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data6 _$Data6FromJson(Map<String, dynamic> json) => Data6(
  storeId: (json['store_id'] as num).toInt(),
  branches: (json['branches'] as List<dynamic>)
      .map((e) => Branches.fromJson(e as Map<String, dynamic>))
      .toList(),
  generatedAt: json['generated_at'] as String?,
);

Map<String, dynamic> _$Data6ToJson(Data6 instance) => <String, dynamic>{
  'store_id': instance.storeId,
  'branches': instance.branches,
  'generated_at': instance.generatedAt,
};
