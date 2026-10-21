// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fiscal.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Fiscal _$FiscalFromJson(Map<String, dynamic> json) => Fiscal(
  status: json['status'] as String,
  problems: (json['problems'] as List<dynamic>)
      .map((e) => e as String)
      .toList(),
);

Map<String, dynamic> _$FiscalToJson(Fiscal instance) => <String, dynamic>{
  'status': instance.status,
  'problems': instance.problems,
};
