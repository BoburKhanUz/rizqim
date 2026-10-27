// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data133.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data133 _$Data133FromJson(Map<String, dynamic> json) => Data133(
  userId: (json['user_id'] as num).toInt(),
  staffCode: json['staff_code'] as String?,
  pinSetAt: json['pin_set_at'] as String?,
);

Map<String, dynamic> _$Data133ToJson(Data133 instance) => <String, dynamic>{
  'user_id': instance.userId,
  'staff_code': instance.staffCode,
  'pin_set_at': instance.pinSetAt,
};
