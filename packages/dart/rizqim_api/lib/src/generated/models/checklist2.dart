// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'checklist2.g.dart';

@JsonSerializable()
class Checklist2 {
  const Checklist2({
    this.oldKassaUnused,
    this.bankTerminalMode,
    this.scanner2d,
  });

  factory Checklist2.fromJson(Map<String, Object?> json) =>
      _$Checklist2FromJson(json);

  @JsonKey(name: 'old_kassa_unused')
  final bool? oldKassaUnused;
  @JsonKey(name: 'bank_terminal_mode')
  final bool? bankTerminalMode;
  @JsonKey(name: 'scanner_2d')
  final bool? scanner2d;

  Map<String, Object?> toJson() => _$Checklist2ToJson(this);
}
