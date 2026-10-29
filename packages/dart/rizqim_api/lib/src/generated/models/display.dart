// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'display.g.dart';

@JsonSerializable()
class Display {
  const Display({
    required this.included,
    required this.unlimited,
    required this.extraPrice,
    required this.canAdd,
  });

  factory Display.fromJson(Map<String, Object?> json) =>
      _$DisplayFromJson(json);

  final dynamic included;
  final bool unlimited;
  @JsonKey(name: 'extra_price')
  final String extraPrice;
  @JsonKey(name: 'can_add')
  final bool canAdd;

  Map<String, Object?> toJson() => _$DisplayToJson(this);
}
