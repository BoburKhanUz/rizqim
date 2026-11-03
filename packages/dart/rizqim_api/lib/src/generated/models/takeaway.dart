// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'packaging.dart';
import 'pickup.dart';
import 'business_day2.dart';

part 'takeaway.g.dart';

@JsonSerializable()
class Takeaway {
  const Takeaway({
    required this.packaging,
    required this.pickup,
    required this.businessDay,
  });

  factory Takeaway.fromJson(Map<String, Object?> json) =>
      _$TakeawayFromJson(json);

  final List<Packaging> packaging;
  final Pickup? pickup;
  @JsonKey(name: 'business_day')
  final BusinessDay2 businessDay;

  Map<String, Object?> toJson() => _$TakeawayToJson(this);
}
