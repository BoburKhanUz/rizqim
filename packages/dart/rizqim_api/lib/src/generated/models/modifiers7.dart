// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'modifiers7.g.dart';

@JsonSerializable()
class Modifiers7 {
  const Modifiers7({required this.modifierId, this.quantity});

  factory Modifiers7.fromJson(Map<String, Object?> json) =>
      _$Modifiers7FromJson(json);

  @JsonKey(name: 'modifier_id')
  final int modifierId;
  final int? quantity;

  Map<String, Object?> toJson() => _$Modifiers7ToJson(this);
}
