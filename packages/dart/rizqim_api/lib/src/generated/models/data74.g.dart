// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data74.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data74 _$Data74FromJson(Map<String, dynamic> json) => Data74(
  userId: (json['user_id'] as num).toInt(),
  staffCode: json['staff_code'] as String?,
  pinSetAt: json['pin_set_at'] as String?,
);

Map<String, dynamic> _$Data74ToJson(Data74 instance) => <String, dynamic>{
  'user_id': instance.userId,
  'staff_code': instance.staffCode,
  'pin_set_at': instance.pinSetAt,
};
