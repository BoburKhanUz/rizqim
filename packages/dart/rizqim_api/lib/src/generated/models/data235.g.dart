// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data235.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data235 _$Data235FromJson(Map<String, dynamic> json) => Data235(
  uuid: json['uuid'] as String,
  name: json['name'] as String,
  phone: json['phone'] as String?,
  code: json['code'] as String,
  dailyLimit: json['daily_limit'] as String?,
  mealsPerDay: (json['meals_per_day'] as num?)?.toInt(),
  isActive: json['is_active'] as bool,
  note: json['note'] as String?,
  createdAt: json['created_at'] as String?,
  updatedAt: json['updated_at'] as String?,
);

Map<String, dynamic> _$Data235ToJson(Data235 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'name': instance.name,
  'phone': instance.phone,
  'code': instance.code,
  'daily_limit': instance.dailyLimit,
  'meals_per_day': instance.mealsPerDay,
  'is_active': instance.isActive,
  'note': instance.note,
  'created_at': instance.createdAt,
  'updated_at': instance.updatedAt,
};
