// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'modifiers5.g.dart';

@JsonSerializable()
class Modifiers5 {
  const Modifiers5({required this.modifierId, this.quantity});

  factory Modifiers5.fromJson(Map<String, Object?> json) =>
      _$Modifiers5FromJson(json);

  @JsonKey(name: 'modifier_id')
  final int modifierId;
  final int? quantity;

  Map<String, Object?> toJson() => _$Modifiers5ToJson(this);
}
