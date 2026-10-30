// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'courses7.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Courses7 _$Courses7FromJson(Map<String, dynamic> json) => Courses7(
  courseNo: (json['course_no'] as num).toInt(),
  status: json['status'] as String,
  items: (json['items'] as num).toInt(),
  sent: (json['sent'] as num).toInt(),
  ready: (json['ready'] as num).toInt(),
  served: (json['served'] as num).toInt(),
);

Map<String, dynamic> _$Courses7ToJson(Courses7 instance) => <String, dynamic>{
  'course_no': instance.courseNo,
  'status': instance.status,
  'items': instance.items,
  'sent': instance.sent,
  'ready': instance.ready,
  'served': instance.served,
};
