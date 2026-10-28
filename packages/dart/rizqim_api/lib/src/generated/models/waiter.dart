// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'waiter.g.dart';

@JsonSerializable()
class Waiter {
  const Waiter({
    required this.included,
    required this.unlimited,
    required this.extraPrice,
    required this.canAdd,
  });

  factory Waiter.fromJson(Map<String, Object?> json) => _$WaiterFromJson(json);

  final dynamic included;
  final bool unlimited;
  @JsonKey(name: 'extra_price')
  final String extraPrice;
  @JsonKey(name: 'can_add')
  final bool canAdd;

  Map<String, Object?> toJson() => _$WaiterToJson(this);
}
