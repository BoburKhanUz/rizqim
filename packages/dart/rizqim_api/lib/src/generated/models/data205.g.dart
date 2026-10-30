// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data205.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data205 _$Data205FromJson(Map<String, dynamic> json) => Data205(
  session: json['session'] == null
      ? null
      : Session2.fromJson(json['session'] as Map<String, dynamic>),
  todayMinutes: (json['today_minutes'] as num).toInt(),
  businessDate: json['business_date'] as String,
);

Map<String, dynamic> _$Data205ToJson(Data205 instance) => <String, dynamic>{
  'session': instance.session,
  'today_minutes': instance.todayMinutes,
  'business_date': instance.businessDate,
};
