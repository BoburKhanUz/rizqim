// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fiscal.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Fiscal _$FiscalFromJson(Map<String, dynamic> json) => Fiscal(
  single: (json['single'] as List<dynamic>)
      .map((e) => Single.fromJson(e as Map<String, dynamic>))
      .toList(),
  split: (json['split'] as List<dynamic>)
      .map((e) => Split.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$FiscalToJson(Fiscal instance) => <String, dynamic>{
  'single': instance.single,
  'split': instance.split,
};
