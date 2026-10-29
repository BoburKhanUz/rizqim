// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'modules2.g.dart';

@JsonSerializable()
class Modules2 {
  const Modules2({required this.customerDisplay, required this.queueCalling});

  factory Modules2.fromJson(Map<String, Object?> json) =>
      _$Modules2FromJson(json);

  @JsonKey(name: 'customer_display')
  final bool customerDisplay;
  @JsonKey(name: 'queue_calling')
  final bool queueCalling;

  Map<String, Object?> toJson() => _$Modules2ToJson(this);
}
