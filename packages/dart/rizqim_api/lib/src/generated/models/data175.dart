// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'type9.dart';

part 'data175.g.dart';

@JsonSerializable()
class Data175 {
  const Data175({
    required this.id,
    required this.code,
    required this.type,
    required this.amount,
    required this.discountAmount,
    required this.description,
  });

  factory Data175.fromJson(Map<String, Object?> json) =>
      _$Data175FromJson(json);

  final int id;
  final String code;
  final Type9 type;
  final num amount;
  @JsonKey(name: 'discount_amount')
  final num? discountAmount;
  final String description;

  Map<String, Object?> toJson() => _$Data175ToJson(this);
}
