// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'items51.g.dart';

@JsonSerializable()
class Items51 {
  const Items51({
    required this.productId,
    required this.productName,
    required this.unit,
    required this.reason,
    required this.reasonLabel,
    required this.quantity,
  });

  factory Items51.fromJson(Map<String, Object?> json) =>
      _$Items51FromJson(json);

  @JsonKey(name: 'product_id')
  final int productId;
  @JsonKey(name: 'product_name')
  final String? productName;
  final String unit;
  final String reason;
  @JsonKey(name: 'reason_label')
  final String reasonLabel;
  final num quantity;

  Map<String, Object?> toJson() => _$Items51ToJson(this);
}
