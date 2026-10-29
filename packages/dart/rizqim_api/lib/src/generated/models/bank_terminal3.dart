// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'bank_terminal3.g.dart';

@JsonSerializable()
class BankTerminal3 {
  const BankTerminal3({this.total, this.count, this.terminalRef, this.note});

  factory BankTerminal3.fromJson(Map<String, Object?> json) =>
      _$BankTerminal3FromJson(json);

  final num? total;
  final int? count;
  @JsonKey(name: 'terminal_ref')
  final String? terminalRef;
  final String? note;

  Map<String, Object?> toJson() => _$BankTerminal3ToJson(this);
}
