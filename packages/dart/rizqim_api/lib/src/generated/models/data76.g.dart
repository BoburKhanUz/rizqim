// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data76.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data76 _$Data76FromJson(Map<String, dynamic> json) => Data76(
  userId: (json['user_id'] as num).toInt(),
  staffCode: json['staff_code'] as String?,
  pinSetAt: json['pin_set_at'] as String?,
);

Map<String, dynamic> _$Data76ToJson(Data76 instance) => <String, dynamic>{
  'user_id': instance.userId,
  'staff_code': instance.staffCode,
  'pin_set_at': instance.pinSetAt,
};
