// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data158.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data158 _$Data158FromJson(Map<String, dynamic> json) => Data158(
  uuid: json['uuid'] as String,
  branchId: (json['branch_id'] as num).toInt(),
  userId: (json['user_id'] as num).toInt(),
  userName: json['user_name'] as String?,
  businessDate: json['business_date'] as String,
  clockInAt: json['clock_in_at'] as String,
  clockOutAt: json['clock_out_at'] as String?,
  abandoned: json['abandoned'] as bool,
  open: json['open'] as bool,
  minutes: (json['minutes'] as num).toInt(),
  source: json['source'] as String,
  note: json['note'] as String?,
);

Map<String, dynamic> _$Data158ToJson(Data158 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'branch_id': instance.branchId,
  'user_id': instance.userId,
  'user_name': instance.userName,
  'business_date': instance.businessDate,
  'clock_in_at': instance.clockInAt,
  'clock_out_at': instance.clockOutAt,
  'abandoned': instance.abandoned,
  'open': instance.open,
  'minutes': instance.minutes,
  'source': instance.source,
  'note': instance.note,
};
