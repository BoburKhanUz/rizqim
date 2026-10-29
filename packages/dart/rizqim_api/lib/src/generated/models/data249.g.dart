// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data249.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data249 _$Data249FromJson(Map<String, dynamic> json) => Data249(
  shiftUuid: json['shift_uuid'] as String?,
  businessDate: json['business_date'] as String,
  ownOnly: json['own_only'] as bool,
  summary: Summary6.fromJson(json['summary'] as Map<String, dynamic>),
  tips: (json['tips'] as List<dynamic>)
      .map((e) => Tips14.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data249ToJson(Data249 instance) => <String, dynamic>{
  'shift_uuid': instance.shiftUuid,
  'business_date': instance.businessDate,
  'own_only': instance.ownOnly,
  'summary': instance.summary,
  'tips': instance.tips,
};
