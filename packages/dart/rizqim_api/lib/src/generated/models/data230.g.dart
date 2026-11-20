// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data230.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data230 _$Data230FromJson(Map<String, dynamic> json) => Data230(
  contractNo: json['contract_no'] as String?,
  text: json['text'] as String,
  lines: (json['lines'] as List<dynamic>).map((e) => e as String).toList(),
  fields: Map<String, String>.from(json['fields'] as Map),
  warnings: (json['warnings'] as List<dynamic>)
      .map((e) => Warnings12.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data230ToJson(Data230 instance) => <String, dynamic>{
  'contract_no': instance.contractNo,
  'text': instance.text,
  'lines': instance.lines,
  'fields': instance.fields,
  'warnings': instance.warnings,
};
