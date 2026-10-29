// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'categories.dart';
import 'products5.dart';

part 'station_routes.g.dart';

@JsonSerializable()
class StationRoutes {
  const StationRoutes({required this.categories, required this.products});

  factory StationRoutes.fromJson(Map<String, Object?> json) =>
      _$StationRoutesFromJson(json);

  final List<Categories> categories;
  final List<Products5> products;

  Map<String, Object?> toJson() => _$StationRoutesToJson(this);
}
