// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data189.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data189 _$Data189FromJson(Map<String, dynamic> json) => Data189(
  session: json['session'] == null
      ? null
      : Session2.fromJson(json['session'] as Map<String, dynamic>),
  todayMinutes: (json['today_minutes'] as num).toInt(),
  businessDate: json['business_date'] as String,
);

Map<String, dynamic> _$Data189ToJson(Data189 instance) => <String, dynamic>{
  'session': instance.session,
  'today_minutes': instance.todayMinutes,
  'business_date': instance.businessDate,
};
