// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data112.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data112 _$Data112FromJson(Map<String, dynamic> json) => Data112(
  userId: (json['user_id'] as num).toInt(),
  staffCode: json['staff_code'] as String?,
  pinSetAt: json['pin_set_at'] as String?,
);

Map<String, dynamic> _$Data112ToJson(Data112 instance) => <String, dynamic>{
  'user_id': instance.userId,
  'staff_code': instance.staffCode,
  'pin_set_at': instance.pinSetAt,
};
