// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data50.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data50 _$Data50FromJson(Map<String, dynamic> json) => Data50(
  userId: (json['user_id'] as num).toInt(),
  staffCode: json['staff_code'] as String?,
  pinSetAt: json['pin_set_at'] as String?,
);

Map<String, dynamic> _$Data50ToJson(Data50 instance) => <String, dynamic>{
  'user_id': instance.userId,
  'staff_code': instance.staffCode,
  'pin_set_at': instance.pinSetAt,
};
