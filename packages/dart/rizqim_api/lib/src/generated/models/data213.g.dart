// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data213.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data213 _$Data213FromJson(Map<String, dynamic> json) => Data213(
  session: json['session'] == null
      ? null
      : Session3.fromJson(json['session'] as Map<String, dynamic>),
  todayMinutes: (json['today_minutes'] as num).toInt(),
  businessDate: json['business_date'] as String,
);

Map<String, dynamic> _$Data213ToJson(Data213 instance) => <String, dynamic>{
  'session': instance.session,
  'today_minutes': instance.todayMinutes,
  'business_date': instance.businessDate,
};
