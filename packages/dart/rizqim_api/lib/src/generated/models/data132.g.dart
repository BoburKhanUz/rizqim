// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data132.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data132 _$Data132FromJson(Map<String, dynamic> json) => Data132(
  userId: (json['user_id'] as num).toInt(),
  staffCode: json['staff_code'] as String?,
  pinSetAt: json['pin_set_at'] as String?,
);

Map<String, dynamic> _$Data132ToJson(Data132 instance) => <String, dynamic>{
  'user_id': instance.userId,
  'staff_code': instance.staffCode,
  'pin_set_at': instance.pinSetAt,
};
