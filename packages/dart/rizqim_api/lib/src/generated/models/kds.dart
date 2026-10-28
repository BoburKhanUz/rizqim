// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'kds.g.dart';

@JsonSerializable()
class Kds {
  const Kds({
    required this.included,
    required this.active,
    required this.extra,
    required this.extraPrice,
    required this.canAdd,
  });

  factory Kds.fromJson(Map<String, Object?> json) => _$KdsFromJson(json);

  final int? included;
  final int active;
  final int extra;
  @JsonKey(name: 'extra_price')
  final String? extraPrice;
  @JsonKey(name: 'can_add')
  final bool canAdd;

  Map<String, Object?> toJson() => _$KdsToJson(this);
}
