// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data297.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data297 _$Data297FromJson(Map<String, dynamic> json) => Data297(
  shiftUuid: json['shift_uuid'] as String?,
  businessDate: json['business_date'] as String,
  ownOnly: json['own_only'] as bool,
  summary: Summary7.fromJson(json['summary'] as Map<String, dynamic>),
  tips: (json['tips'] as List<dynamic>)
      .map((e) => Tips15.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data297ToJson(Data297 instance) => <String, dynamic>{
  'shift_uuid': instance.shiftUuid,
  'business_date': instance.businessDate,
  'own_only': instance.ownOnly,
  'summary': instance.summary,
  'tips': instance.tips,
};
