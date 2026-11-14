// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'lines26.g.dart';

@JsonSerializable()
class Lines26 {
  const Lines26({
    required this.uuid,
    required this.kind,
    required this.kindLabel,
    required this.name,
    required this.extraId,
    required this.hallId,
    required this.quantity,
    required this.persons,
    required this.perPerson,
    required this.unitPrice,
    required this.amount,
    required this.frozen,
    required this.note,
  });

  factory Lines26.fromJson(Map<String, Object?> json) =>
      _$Lines26FromJson(json);

  final String uuid;
  final String kind;
  @JsonKey(name: 'kind_label')
  final String kindLabel;
  final String name;
  @JsonKey(name: 'extra_id')
  final int? extraId;
  @JsonKey(name: 'hall_id')
  final int? hallId;
  final String quantity;
  final int? persons;
  @JsonKey(name: 'per_person')
  final bool perPerson;
  @JsonKey(name: 'unit_price')
  final String unitPrice;
  final String amount;
  final bool frozen;
  final String? note;

  Map<String, Object?> toJson() => _$Lines26ToJson(this);
}
