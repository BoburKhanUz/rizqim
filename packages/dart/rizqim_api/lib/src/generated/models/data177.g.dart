// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data177.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data177 _$Data177FromJson(Map<String, dynamic> json) => Data177(
  userId: (json['user_id'] as num).toInt(),
  staffCode: json['staff_code'] as String?,
  pinSetAt: json['pin_set_at'] as String?,
);

Map<String, dynamic> _$Data177ToJson(Data177 instance) => <String, dynamic>{
  'user_id': instance.userId,
  'staff_code': instance.staffCode,
  'pin_set_at': instance.pinSetAt,
};
