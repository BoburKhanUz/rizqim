// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data218.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data218 _$Data218FromJson(Map<String, dynamic> json) => Data218(
  userId: (json['user_id'] as num).toInt(),
  staffCode: json['staff_code'] as String?,
  pinSetAt: json['pin_set_at'] as String?,
);

Map<String, dynamic> _$Data218ToJson(Data218 instance) => <String, dynamic>{
  'user_id': instance.userId,
  'staff_code': instance.staffCode,
  'pin_set_at': instance.pinSetAt,
};
