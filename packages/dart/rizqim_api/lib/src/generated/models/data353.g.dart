// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data353.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data353 _$Data353FromJson(Map<String, dynamic> json) => Data353(
  shiftUuid: json['shift_uuid'] as String?,
  businessDate: json['business_date'] as String,
  ownOnly: json['own_only'] as bool,
  summary: Summary13.fromJson(json['summary'] as Map<String, dynamic>),
  tips: (json['tips'] as List<dynamic>)
      .map((e) => Tips16.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data353ToJson(Data353 instance) => <String, dynamic>{
  'shift_uuid': instance.shiftUuid,
  'business_date': instance.businessDate,
  'own_only': instance.ownOnly,
  'summary': instance.summary,
  'tips': instance.tips,
};
