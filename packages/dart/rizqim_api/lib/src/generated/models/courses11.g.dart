// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'courses11.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Courses11 _$Courses11FromJson(Map<String, dynamic> json) => Courses11(
  courseNo: (json['course_no'] as num).toInt(),
  status: json['status'] as String,
  items: (json['items'] as num).toInt(),
  sent: (json['sent'] as num).toInt(),
  ready: (json['ready'] as num).toInt(),
  served: (json['served'] as num).toInt(),
);

Map<String, dynamic> _$Courses11ToJson(Courses11 instance) => <String, dynamic>{
  'course_no': instance.courseNo,
  'status': instance.status,
  'items': instance.items,
  'sent': instance.sent,
  'ready': instance.ready,
  'served': instance.served,
};
