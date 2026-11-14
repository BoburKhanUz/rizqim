// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'lines24.g.dart';

@JsonSerializable()
class Lines24 {
  const Lines24({
    required this.uuid,
    required this.kind,
    required this.name,
    required this.unitPrice,
    required this.quantity,
    required this.persons,
    required this.amount,
  });

  factory Lines24.fromJson(Map<String, Object?> json) =>
      _$Lines24FromJson(json);

  final String uuid;
  final String kind;
  final String name;
  @JsonKey(name: 'unit_price')
  final String unitPrice;
  final String quantity;
  final int? persons;
  final String amount;

  Map<String, Object?> toJson() => _$Lines24ToJson(this);
}
