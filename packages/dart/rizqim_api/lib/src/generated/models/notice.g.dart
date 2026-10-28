// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'notice.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Notice _$NoticeFromJson(Map<String, dynamic> json) => Notice(
  level: json['level'] as String,
  message: json['message'] as String,
  daysOverdue: (json['days_overdue'] as num).toInt(),
  lockAt: json['lock_at'] as String?,
  invoice: json['invoice'] == null
      ? null
      : Invoice.fromJson(json['invoice'] as Map<String, dynamic>),
);

Map<String, dynamic> _$NoticeToJson(Notice instance) => <String, dynamic>{
  'level': instance.level,
  'message': instance.message,
  'days_overdue': instance.daysOverdue,
  'lock_at': instance.lockAt,
  'invoice': instance.invoice,
};
