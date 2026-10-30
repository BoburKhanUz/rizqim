// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data269.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data269 _$Data269FromJson(Map<String, dynamic> json) => Data269(
  shiftUuid: json['shift_uuid'] as String?,
  businessDate: json['business_date'] as String,
  ownOnly: json['own_only'] as bool,
  summary: Summary7.fromJson(json['summary'] as Map<String, dynamic>),
  tips: (json['tips'] as List<dynamic>)
      .map((e) => Tips14.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data269ToJson(Data269 instance) => <String, dynamic>{
  'shift_uuid': instance.shiftUuid,
  'business_date': instance.businessDate,
  'own_only': instance.ownOnly,
  'summary': instance.summary,
  'tips': instance.tips,
};
