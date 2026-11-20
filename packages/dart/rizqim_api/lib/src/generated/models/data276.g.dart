// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data276.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data276 _$Data276FromJson(Map<String, dynamic> json) => Data276(
  session: json['session'] == null
      ? null
      : Session3.fromJson(json['session'] as Map<String, dynamic>),
  todayMinutes: (json['today_minutes'] as num).toInt(),
  businessDate: json['business_date'] as String,
);

Map<String, dynamic> _$Data276ToJson(Data276 instance) => <String, dynamic>{
  'session': instance.session,
  'today_minutes': instance.todayMinutes,
  'business_date': instance.businessDate,
};
