// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fiscal2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Fiscal2 _$Fiscal2FromJson(Map<String, dynamic> json) => Fiscal2(
  status: json['status'] as String,
  problems: (json['problems'] as List<dynamic>)
      .map((e) => e as String)
      .toList(),
);

Map<String, dynamic> _$Fiscal2ToJson(Fiscal2 instance) => <String, dynamic>{
  'status': instance.status,
  'problems': instance.problems,
};
