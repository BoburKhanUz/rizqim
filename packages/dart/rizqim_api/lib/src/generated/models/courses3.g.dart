// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'courses3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Courses3 _$Courses3FromJson(Map<String, dynamic> json) => Courses3(
  courseNo: (json['course_no'] as num).toInt(),
  status: json['status'] as String,
  items: (json['items'] as num).toInt(),
  sent: (json['sent'] as num).toInt(),
  ready: (json['ready'] as num).toInt(),
  served: (json['served'] as num).toInt(),
);

Map<String, dynamic> _$Courses3ToJson(Courses3 instance) => <String, dynamic>{
  'course_no': instance.courseNo,
  'status': instance.status,
  'items': instance.items,
  'sent': instance.sent,
  'ready': instance.ready,
  'served': instance.served,
};
