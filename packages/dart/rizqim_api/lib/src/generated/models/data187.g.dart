// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data187.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data187 _$Data187FromJson(Map<String, dynamic> json) => Data187(
  userId: (json['user_id'] as num).toInt(),
  staffCode: json['staff_code'] as String?,
  pinSetAt: json['pin_set_at'] as String?,
);

Map<String, dynamic> _$Data187ToJson(Data187 instance) => <String, dynamic>{
  'user_id': instance.userId,
  'staff_code': instance.staffCode,
  'pin_set_at': instance.pinSetAt,
};
