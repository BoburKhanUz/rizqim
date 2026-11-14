// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'lines22.g.dart';

@JsonSerializable()
class Lines22 {
  const Lines22({
    required this.uuid,
    required this.kind,
    required this.name,
    required this.unitPrice,
    required this.quantity,
    required this.persons,
    required this.amount,
  });

  factory Lines22.fromJson(Map<String, Object?> json) =>
      _$Lines22FromJson(json);

  final String uuid;
  final String kind;
  final String name;
  @JsonKey(name: 'unit_price')
  final String unitPrice;
  final String quantity;
  final int? persons;
  final String amount;

  Map<String, Object?> toJson() => _$Lines22ToJson(this);
}
