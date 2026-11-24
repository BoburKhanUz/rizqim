// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'warnings14.dart';

part 'data236.g.dart';

@JsonSerializable()
class Data236 {
  const Data236({
    required this.contractNo,
    required this.text,
    required this.lines,
    required this.fields,
    required this.warnings,
  });

  factory Data236.fromJson(Map<String, Object?> json) =>
      _$Data236FromJson(json);

  @JsonKey(name: 'contract_no')
  final String? contractNo;
  final String text;
  final List<String> lines;
  final Map<String, String> fields;
  final List<Warnings14> warnings;

  Map<String, Object?> toJson() => _$Data236ToJson(this);
}
