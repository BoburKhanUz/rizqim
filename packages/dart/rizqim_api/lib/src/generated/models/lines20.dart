// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'lines20.g.dart';

@JsonSerializable()
class Lines20 {
  const Lines20({
    required this.uuid,
    required this.kind,
    required this.name,
    required this.unitPrice,
    required this.quantity,
    required this.persons,
    required this.amount,
  });

  factory Lines20.fromJson(Map<String, Object?> json) =>
      _$Lines20FromJson(json);

  final String uuid;
  final String kind;
  final String name;
  @JsonKey(name: 'unit_price')
  final String unitPrice;
  final String quantity;
  final int? persons;
  final String amount;

  Map<String, Object?> toJson() => _$Lines20ToJson(this);
}
