// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'warnings.g.dart';

@JsonSerializable()
class Warnings {
  const Warnings({
    required this.reason,
    required this.device,
    required this.firstSeq,
    required this.serverLastSeq,
    required this.message,
  });

  factory Warnings.fromJson(Map<String, Object?> json) =>
      _$WarningsFromJson(json);

  final String reason;
  final String device;
  @JsonKey(name: 'first_seq')
  final int firstSeq;
  @JsonKey(name: 'server_last_seq')
  final int serverLastSeq;
  final String message;

  Map<String, Object?> toJson() => _$WarningsToJson(this);
}
