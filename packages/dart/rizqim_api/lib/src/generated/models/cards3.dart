// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'cards3.g.dart';

@JsonSerializable()
class Cards3 {
  const Cards3({
    required this.cardType,
    required this.count,
    required this.amount,
  });

  factory Cards3.fromJson(Map<String, Object?> json) => _$Cards3FromJson(json);

  @JsonKey(name: 'card_type')
  final String cardType;
  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$Cards3ToJson(this);
}
