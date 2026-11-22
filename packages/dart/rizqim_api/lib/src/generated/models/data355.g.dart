// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data355.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data355 _$Data355FromJson(Map<String, dynamic> json) => Data355(
  shiftUuid: json['shift_uuid'] as String?,
  businessDate: json['business_date'] as String,
  ownOnly: json['own_only'] as bool,
  summary: Summary13.fromJson(json['summary'] as Map<String, dynamic>),
  tips: (json['tips'] as List<dynamic>)
      .map((e) => Tips16.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data355ToJson(Data355 instance) => <String, dynamic>{
  'shift_uuid': instance.shiftUuid,
  'business_date': instance.businessDate,
  'own_only': instance.ownOnly,
  'summary': instance.summary,
  'tips': instance.tips,
};
