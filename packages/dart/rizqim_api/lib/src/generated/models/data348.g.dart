// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data348.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data348 _$Data348FromJson(Map<String, dynamic> json) => Data348(
  shiftUuid: json['shift_uuid'] as String?,
  businessDate: json['business_date'] as String,
  ownOnly: json['own_only'] as bool,
  summary: Summary12.fromJson(json['summary'] as Map<String, dynamic>),
  tips: (json['tips'] as List<dynamic>)
      .map((e) => Tips16.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data348ToJson(Data348 instance) => <String, dynamic>{
  'shift_uuid': instance.shiftUuid,
  'business_date': instance.businessDate,
  'own_only': instance.ownOnly,
  'summary': instance.summary,
  'tips': instance.tips,
};
