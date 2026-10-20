// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'terminals.g.dart';

@JsonSerializable()
class Terminals {
  const Terminals({required this.activeNow, required this.counted});

  factory Terminals.fromJson(Map<String, Object?> json) =>
      _$TerminalsFromJson(json);

  @JsonKey(name: 'active_now')
  final int activeNow;
  final int? counted;

  Map<String, Object?> toJson() => _$TerminalsToJson(this);
}
