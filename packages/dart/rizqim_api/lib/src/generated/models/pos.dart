// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'pos.g.dart';

@JsonSerializable()
class Pos {
  const Pos({
    required this.included,
    required this.active,
    required this.extra,
    required this.extraPrice,
    required this.canAdd,
  });

  factory Pos.fromJson(Map<String, Object?> json) => _$PosFromJson(json);

  final int? included;
  final int active;
  final int extra;
  @JsonKey(name: 'extra_price')
  final String? extraPrice;
  @JsonKey(name: 'can_add')
  final bool canAdd;

  Map<String, Object?> toJson() => _$PosToJson(this);
}
