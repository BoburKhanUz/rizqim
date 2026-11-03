// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fiscal3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Fiscal3 _$Fiscal3FromJson(Map<String, dynamic> json) => Fiscal3(
  status: json['status'] as String,
  problems: (json['problems'] as List<dynamic>)
      .map((e) => e as String)
      .toList(),
);

Map<String, dynamic> _$Fiscal3ToJson(Fiscal3 instance) => <String, dynamic>{
  'status': instance.status,
  'problems': instance.problems,
};
