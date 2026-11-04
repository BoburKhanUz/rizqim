// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data275.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data275 _$Data275FromJson(Map<String, dynamic> json) => Data275(
  shiftUuid: json['shift_uuid'] as String?,
  businessDate: json['business_date'] as String,
  ownOnly: json['own_only'] as bool,
  summary: Summary7.fromJson(json['summary'] as Map<String, dynamic>),
  tips: (json['tips'] as List<dynamic>)
      .map((e) => Tips14.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data275ToJson(Data275 instance) => <String, dynamic>{
  'shift_uuid': instance.shiftUuid,
  'business_date': instance.businessDate,
  'own_only': instance.ownOnly,
  'summary': instance.summary,
  'tips': instance.tips,
};
