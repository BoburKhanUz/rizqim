// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'warnings12.dart';

part 'data228.g.dart';

@JsonSerializable()
class Data228 {
  const Data228({
    required this.contractNo,
    required this.text,
    required this.lines,
    required this.fields,
    required this.warnings,
  });

  factory Data228.fromJson(Map<String, Object?> json) =>
      _$Data228FromJson(json);

  @JsonKey(name: 'contract_no')
  final String? contractNo;
  final String text;
  final List<String> lines;
  final Map<String, String> fields;
  final List<Warnings12> warnings;

  Map<String, Object?> toJson() => _$Data228ToJson(this);
}
