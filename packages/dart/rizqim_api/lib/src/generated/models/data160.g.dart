// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data160.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data160 _$Data160FromJson(Map<String, dynamic> json) => Data160(
  userId: (json['user_id'] as num).toInt(),
  staffCode: json['staff_code'] as String?,
  pinSetAt: json['pin_set_at'] as String?,
);

Map<String, dynamic> _$Data160ToJson(Data160 instance) => <String, dynamic>{
  'user_id': instance.userId,
  'staff_code': instance.staffCode,
  'pin_set_at': instance.pinSetAt,
};
