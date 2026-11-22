// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'warnings14.dart';

part 'data235.g.dart';

@JsonSerializable()
class Data235 {
  const Data235({
    required this.contractNo,
    required this.text,
    required this.lines,
    required this.fields,
    required this.warnings,
  });

  factory Data235.fromJson(Map<String, Object?> json) =>
      _$Data235FromJson(json);

  @JsonKey(name: 'contract_no')
  final String? contractNo;
  final String text;
  final List<String> lines;
  final Map<String, String> fields;
  final List<Warnings14> warnings;

  Map<String, Object?> toJson() => _$Data235ToJson(this);
}
