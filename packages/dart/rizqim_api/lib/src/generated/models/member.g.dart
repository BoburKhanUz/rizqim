// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'member.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Member _$MemberFromJson(Map<String, dynamic> json) => Member(
  uuid: json['uuid'] as String,
  name: json['name'] as String,
  phone: json['phone'] as String?,
  code: json['code'] as String,
  dailyLimit: json['daily_limit'] as String?,
  mealsPerDay: (json['meals_per_day'] as num?)?.toInt(),
  isActive: json['is_active'] as bool,
);

Map<String, dynamic> _$MemberToJson(Member instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'name': instance.name,
  'phone': instance.phone,
  'code': instance.code,
  'daily_limit': instance.dailyLimit,
  'meals_per_day': instance.mealsPerDay,
  'is_active': instance.isActive,
};
